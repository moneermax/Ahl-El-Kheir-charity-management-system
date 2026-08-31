<?php
// modules/accounting/fm_review_queue.php - Financial Manager Review Queue (v7: Full Drop-in, Bulletproof SQL)
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/functions.php';
require_once dirname(__DIR__, 2) . '/config/session.php';
require_once __DIR__ . '/lib.php';

Session::start();

$role = Session::getUserRole();
$allowed_fm = ['financial_manager', 'fm', 'finance', 'admin', 'sudo', 'general_manager', 'vice_general_manager'];
if (!in_array($role, $allowed_fm, true)) {
    header('Location: ' . APP_URL . 'index.php');
    exit();
}

$pageTitle = 'طابور المراجعة المالية';
$active = 'fm_review';

if (function_exists('ak_ensure_tables')) ak_ensure_tables(); 
if (function_exists('ak_seed_accounts')) ak_seed_accounts();

$purposeLabels = [
    'monthly_sponsorship' => 'كفالة شهرية',
    'school_fees' => 'رسوم دراسية',
    'medicine' => 'علاج وأدوية',
    'gift' => 'هدية/عيدية',
    'other' => 'أخرى',
    'admin_fee' => 'رسوم إدارية',
    'general_donation' => 'تبرع عام',
    'project_donation' => 'تبرع مشروع',
];

$typeMap = [
    'monthly_sponsorship' => 'sponsorship_payment',
    'school_fees' => 'general_donation',
    'medicine' => 'general_donation',
    'gift' => 'general_donation',
    'other' => 'other',
    'admin_fee' => 'admin_fee',
    'general_donation' => 'general_donation',
    'project_donation' => 'project_donation',
];

$uid = (int)Session::getUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    
    /* --- APPROVE PAYMENT --- */
    if (isset($_POST['approve_payment'])) {
        $sp_id = (int)$_POST['approve_payment'];
        $sp = dbFetchOne("SELECT * FROM sponsor_payments WHERE id = ? AND status = 'pending'", [$sp_id]);
        
        if ($sp) {
            $purLabel = $purposeLabels[$sp['payment_type']] ?? $sp['payment_type'];
            $txnType = $typeMap[$sp['payment_type']] ?? 'other';
            $desc = 'تحصيل مشرف (' . $purLabel . ')' . ($sp['payment_period'] ? ' للفترة: ' . $sp['payment_period'] : '') . ($sp['purpose_note'] ? ' — ' . $sp['purpose_note'] : '');
            $txnCode = 'SP-' . str_pad((string)$sp['id'], 6, '0', STR_PAD_LEFT);

            // 🛡️ Bulletproof math to satisfy database constraint
            $amount = (float)$sp['amount'];
            $admin_fee_percent = 0.00;
            $admin_fee_amount = 0.00;
            $net_amount = $amount; 

            $sql_insert = "INSERT INTO transactions ";
            $sql_insert .= "(sponsorship_id, amount, currency_code, payment_method, transaction_date, receipt_number, description, ";
            $sql_insert .= "months_covered, transaction_type, receipt_path, unified_receipt_path, status, created_by, transaction_code, ";
            $sql_insert .= "sponsor_id, project_id, payment_period, purpose_note, purpose, admin_fee_percent, admin_fee_amount, net_amount) ";
            $sql_insert .= "VALUES (?, ?, ?, 'other', ?, ?, ?, 1.00, ?, ?, ?, 'posted', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            dbExecute($sql_insert, [
                $sp['sponsorship_id'], $amount, $sp['currency_code'], date('Y-m-d', strtotime($sp['created_at'])),
                $txnCode, $desc, $txnType, $sp['receipt_file_path'], $sp['unified_receipt_path'], $uid, $txnCode,
                $sp['sponsor_id'], $sp['project_id'], $sp['payment_period'], $sp['purpose_note'], $sp['payment_type'],
                $admin_fee_percent, $admin_fee_amount, $net_amount
            ]);

            $txnId = (int)dbLastInsertId();
            
            // Post Journal Entry
            if (function_exists('ak_post_transaction_journal')) {
                try { ak_post_transaction_journal($txnId); } catch (Throwable $e) {}
            }

            // Update original payment record
            dbExecute("UPDATE sponsor_payments SET status = 'approved', reviewed_by_user_id = ?, reviewed_at = NOW(), transaction_id = ? WHERE id = ?",
                [$uid, $txnId, $sp_id]);

            // Audit Log
            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                    VALUES (?, 'APPROVE', 'sponsor_payments', ?, ?, ?, ?, ?)",
                    [
                        $uid, $sp_id, 
                        json_encode(['status' => 'pending']), 
                        json_encode(['status' => 'approved', 'txn_id' => $txnId, 'type' => $txnType], JSON_UNESCAPED_UNICODE), 
                        $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
            } catch (Throwable $e) {}

            // Notify GM/VGM
            try {
                $gm = dbFetchAll("SELECT id FROM users WHERE role_id IN (2,3) AND is_active = 1");
                foreach ($gm as $u) {
                    dbExecute("INSERT INTO notifications (user_id, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())",
                        [$u['id'], 'اعتماد تحصيل مشرف', 'تم اعتماد تحصيل (' . $purLabel . ') بمبلغ ' . number_format($amount, 0) . ' بواسطة المدير المالي.', 'modules/accounting/fm_review_queue.php']);
                }
            } catch (Throwable $e) {}

            flash('success', 'تم اعتماد الدفعة وترحيلها للخزينة والقيود المحاسبية بنجاح.');
        }
        header('Location: ' . APP_URL . 'modules/accounting/fm_review_queue.php'); exit;
    }

    /* --- RETURN PAYMENT --- */
    if (isset($_POST['return_payment'])) {
        $sp_id = (int)$_POST['return_payment'];
        $note = trim($_POST['return_note_' . $sp_id] ?? '');
        if ($note === '') {
            flash('error', 'يجب كتابة سبب الإرجاع.');
        } else {
            dbExecute("UPDATE sponsor_payments SET status = 'returned', reviewed_by_user_id = ?, reviewed_at = NOW(), return_note = ? WHERE id = ? AND status = 'pending'",
                [$uid, $note, $sp_id]);
            
            try {
                dbExecute("INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                    VALUES (?, 'RETURN', 'sponsor_payments', ?, ?, ?, ?, ?)",
                    [$uid, $sp_id, json_encode(['status' => 'pending']), json_encode(['status' => 'returned', 'note' => $note], JSON_UNESCAPED_UNICODE), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            } catch (Throwable $e) {}

            flash('success', 'تم إرجاع الدفعة للمشرف.');
        }
        header('Location: ' . APP_URL . 'modules/accounting/fm_review_queue.php'); exit;
    }
}

// 🛡️ Bulletproof SQL with explicit spacing to prevent syntax errors
$sql_payments = "SELECT sp.*, u.full_name AS supervisor_name, spn.full_name AS sponsor_name, spn.sponsor_code, fc.child_name ";
$sql_payments .= "FROM sponsor_payments sp ";
$sql_payments .= "JOIN users u ON u.id = sp.supervisor_id ";
$sql_payments .= "LEFT JOIN sponsorships spon ON spon.id = sp.sponsorship_id ";
$sql_payments .= "LEFT JOIN sponsors spn ON spn.id = COALESCE(sp.sponsor_id, spon.sponsor_id) ";
$sql_payments .= "LEFT JOIN family_children fc ON fc.id = spon.child_id ";
$sql_payments .= "WHERE sp.status = 'pending' ";
$sql_payments .= "ORDER BY sp.created_at ASC";
$payments = dbFetchAll($sql_payments);

$sql_history = "SELECT sp.*, u.full_name AS supervisor_name, spn.full_name AS sponsor_name, rev.full_name AS reviewer_name ";
$sql_history .= "FROM sponsor_payments sp ";
$sql_history .= "JOIN users u ON u.id = sp.supervisor_id ";
$sql_history .= "LEFT JOIN sponsors spn ON spn.id = sp.sponsor_id ";
$sql_history .= "LEFT JOIN users rev ON rev.id = sp.reviewed_by_user_id ";
$sql_history .= "WHERE sp.status IN ('approved', 'returned') ";
$sql_history .= "ORDER BY sp.reviewed_at DESC LIMIT 20";
$history = dbFetchAll($sql_history);

include dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="welcome-section fade-in">
    <h2><i class="fas fa-clipboard-check me-2"></i>طابور المراجعة المالية</h2>
    <p>مراجعة واعتماد تحصيلات المشرفين قبل ترحيلها للخزينة (مكافحة الفساد والتدقيق المالي)</p>
</div>

<?php include dirname(__DIR__, 2) . '/includes/alerts.php'; ?>

<div class="card mb-4 fade-in">
    <div class="card-header text-white" style="background:#1b4d8f">
        <i class="fas fa-hourglass-half me-2"></i>التحصيلات المعلقة بانتظار الاعتماد (<?php echo count($payments); ?>)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>التاريخ</th>
                        <th>المشرف</th>
                        <th>الكفيل / اليتيم</th>
                        <th>الغرض / الفترة</th>
                        <th>المبلغ</th>
                        <th>الإيصالات</th>
                        <th class="text-center" style="width: 30%;">إجراء المراجعة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$payments): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">لا توجد تحصيلات معلقة حالياً.</td></tr>
                    <?php else: foreach ($payments as $p): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($p['created_at'])); ?></td>
                            <td><strong><?php echo e($p['supervisor_name']); ?></strong></td>
                            <td>
                                <?php if ($p['sponsor_name']): ?>
                                    <small class="text-muted">كفيل:</small> <?php echo e($p['sponsor_name']); ?> <code><?php echo e($p['sponsor_code'] ?? ''); ?></code><br>
                                <?php endif; ?>
                                <?php if (!empty($p['child_name'])): ?>
                                    <small class="text-muted">يتيم:</small> <?php echo e($p['child_name']); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo e($purposeLabels[$p['payment_type']] ?? $p['payment_type']); ?></small>
                                <?php if ($p['payment_period']): ?>
                                    <small class="text-muted d-block"><?php echo e($p['payment_period']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo number_format((float)$p['amount'], 0); ?></strong></td>
                            <td>
                                <?php if ($p['receipt_file_path']): ?>
                                    <a href="<?php echo APP_URL . 'modules/transactions/receipt_sp.php?id=' . (int)$p['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="إيصال البند"><i class="fas fa-eye"></i></a>
                                <?php endif; ?>
                                <?php if (!empty($p['unified_receipt_path'])): ?>
                                    <a href="<?php echo APP_URL . 'modules/transactions/receipt_sp.php?id=' . (int)$p['id'] . '&kind=unified'; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="الإيصال الموحّد"><i class="fas fa-layer-group"></i></a>
                                <?php endif; ?>
                                <?php if (!$p['receipt_file_path'] && empty($p['unified_receipt_path'])): ?>
                                    <span class="text-danger">لا يوجد</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" class="d-flex gap-1 align-items-center mb-1">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="approve_payment" value="<?php echo $p['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success w-100" onclick="return confirm('اعتماد الدفعة وترحيلها للخزينة والقيود؟')">
                                        <i class="fas fa-check"></i> اعتماد
                                    </button>
                                </form>
                                <form method="post" class="d-flex gap-1 align-items-center">
                                    <?php echo csrf_field(); ?>
                                    <input type="text" name="return_note_<?php echo $p['id']; ?>" class="form-control form-control-sm" placeholder="سبب الإرجاع..." required>
                                    <button type="submit" name="return_payment" value="<?php echo $p['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('إرجاع الدفعة للمشرف؟')">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card fade-in">
    <div class="card-header"><i class="fas fa-history me-2"></i>سجل المراجعات الأخيرة</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>التاريخ</th>
                        <th>المشرف</th>
                        <th>الكفيل</th>
                        <th>الغرض</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>المراجع</th>
                        <th>الإيصالات</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="9" class="text-center text-muted py-3">لا يوجد سجل.</td></tr>
                    <?php else: foreach ($history as $h): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($h['reviewed_at'])); ?></td>
                            <td><?php echo e($h['supervisor_name']); ?></td>
                            <td><?php echo e($h['sponsor_name'] ?? '—'); ?></td>
                            <td>
                                <small><?php echo e($purposeLabels[$h['payment_type']] ?? $h['payment_type']); ?></small>
                                <?php if ($h['payment_period']): ?>
                                    <small class="text-muted d-block"><?php echo e($h['payment_period']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format((float)$h['amount'], 0); ?></td>
                            <td>
                                <?php echo $h['status'] === 'approved' ? '<span class="badge bg-success">معتمد</span>' : '<span class="badge bg-danger">مرتجع</span>'; ?>
                            </td>
                            <td><?php echo e($h['reviewer_name'] ?? '—'); ?></td>
                            <td class="text-nowrap">
                                <?php if ($h['receipt_file_path']): ?>
                                    <a href="<?php echo APP_URL . 'modules/transactions/receipt_sp.php?id=' . (int)$h['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="إيصال البند"><i class="fas fa-eye"></i></a>
                                <?php endif; ?>
                                <?php if (!empty($h['unified_receipt_path'])): ?>
                                    <a href="<?php echo APP_URL . 'modules/transactions/receipt_sp.php?id=' . (int)$h['id'] . '&kind=unified'; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="الإيصال الموحّد"><i class="fas fa-layer-group"></i></a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($h['return_note'] ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include dirname(__DIR__, 2) . '/includes/footer.php'; ?>