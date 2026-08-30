<?php
/**
 * lib_group_workflow.php
 * Centralized workflow logic for group verification and disbursement
 */

if (!defined('APP_URL')) { exit('Direct access not permitted'); }

// ==========================================
// 1. دوال التحقق (الموجودة مسبقاً)
// ==========================================

function get_latest_verification_status($family_id, $month, $nanny_id) {
    $result = ['is_orphan' => 0, 'is_mother' => 0, 'is_bank' => 0, 'is_fully_verified' => false, 'notes' => '', 'verified_at' => null];
    try {
        $record = dbFetchOne("SELECT is_orphan_verified, is_mother_contact_verified, is_bank_verified, verification_notes, verified_at FROM nanny_family_verifications WHERE family_id = ? AND month = ? AND nanny_id = ? ORDER BY id DESC LIMIT 1", [$family_id, $month, $nanny_id]);
        if ($record) {
            $result['is_orphan'] = (int)($record['is_orphan_verified'] ?? 0);
            $result['is_mother'] = (int)($record['is_mother_contact_verified'] ?? 0);
            $result['is_bank'] = (int)($record['is_bank_verified'] ?? 0);
            $result['notes'] = $record['verification_notes'] ?? '';
            $result['verified_at'] = $record['verified_at'] ?? null;
            $result['is_fully_verified'] = ($result['is_orphan'] === 1 && $result['is_mother'] === 1 && $result['is_bank'] === 1);
        }
    } catch (Throwable $e) { error_log("get_latest_verification_status error: " . $e->getMessage()); }
    return $result;
}

function count_verified_families_in_group($group_id, $month, $nanny_id) {
    $result = ['verified_count' => 0, 'unverified_count' => 0, 'verified_families' => [], 'unverified_families' => [], 'verified_amount' => 0.0, 'unverified_amount' => 0.0];
    try {
        $families = dbFetchAll("SELECT DISTINCT f.id, f.family_code, f.mother_name FROM group_children gc INNER JOIN family_children fc ON fc.id = gc.child_id INNER JOIN families f ON f.id = fc.family_id WHERE gc.group_id = ? AND gc.left_date IS NULL ORDER BY f.family_code ASC", [$group_id]);
        foreach ($families as $family) {
            $status = get_latest_verification_status($family['id'], $month, $nanny_id);
            $amountData = dbFetchOne("SELECT COALESCE(SUM(sp.monthly_amount), 0) as amount FROM sponsorships sp WHERE sp.child_id IN (SELECT fc2.id FROM family_children fc2 WHERE fc2.family_id = ? AND fc2.is_active = 1) AND sp.status = 'active'", [$family['id']]);
            $familyAmount = (float)($amountData['amount'] ?? 0);
            $family['amount'] = $familyAmount;
            $family['status'] = $status;
            if ($status['is_fully_verified']) {
                $result['verified_count']++;
                $result['verified_families'][] = $family;
                $result['verified_amount'] += $familyAmount;
            } else {
                $result['unverified_count']++;
                $result['unverified_families'][] = $family;
                $result['unverified_amount'] += $familyAmount;
            }
        }
    } catch (Throwable $e) { error_log("count_verified_families_in_group error: " . $e->getMessage()); }
    return $result;
}

function can_submit_family($family_id, $month, $nanny_id) {
    $status = get_latest_verification_status($family_id, $month, $nanny_id);
    return $status['is_fully_verified'];
}

function get_verification_badge($is_fully_verified, $group_status = null) {
    return $is_fully_verified ? ['label' => 'موثق', 'color' => 'success', 'icon' => 'fa-check-circle'] : ['label' => 'غير مكتمل', 'color' => 'warning', 'icon' => 'fa-exclamation-triangle'];
}

function get_group_status_badge($status) {
    $map = [
        'pending' => ['قيد التوثيق', 'warning'],
        'submitted' => ['تم الإرسال', 'info'],
        'approved' => ['معتمد', 'success'],
        'transferred' => ['تم التحويل', 'primary'],
        'closed' => ['مغلق', 'secondary'],
        'returned' => ['مُعاد', 'danger']
    ];
    return $map[$status] ?? ['غير معروف', 'dark'];
}

function log_verification_change($family_id, $month, $nanny_id, $old_status, $new_status) {
    try {
        dbExecute("INSERT INTO verification_audit_log (family_id, month, nanny_id, old_orphan, old_mother, old_bank, new_orphan, new_mother, new_bank, changed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())", [
            $family_id, $month, $nanny_id, $old_status['is_orphan'] ?? 0, $old_status['is_mother'] ?? 0, $old_status['is_bank'] ?? 0,
            $new_status['is_orphan'] ?? 0, $new_status['is_mother'] ?? 0, $new_status['is_bank'] ?? 0
        ]);
    } catch (Throwable $e) { error_log("Audit log error: " . $e->getMessage()); }
}

// ==========================================
// 2. دوال سير عمل المحاسب (الجديدة)
// ==========================================

/**
 * جلب المجموعات المقدمة للصرف
 */
function get_submitted_groups_for_accountant($pdo, $month = null) {
    $month = $month ?: date('Y-m');
    $sql = "SELECT g.id, g.group_name, g.verification_status, g.submitted_at, u.full_name AS nanny_name,
            (SELECT COUNT(*) FROM nanny_family_verifications v JOIN group_children gc ON v.family_id = gc.family_id WHERE gc.group_id = g.id AND v.month = ? AND v.is_orphan_verified = 1 AND v.is_mother_contact_verified = 1 AND v.is_bank_verified = 1) AS verified_families_count,
            (SELECT COALESCE(SUM(f.monthly_amount), 0) FROM nanny_family_verifications v JOIN group_children gc ON v.family_id = gc.family_id LEFT JOIN families f ON v.family_id = f.id WHERE gc.group_id = g.id AND v.month = ? AND v.is_orphan_verified = 1 AND v.is_mother_contact_verified = 1 AND v.is_bank_verified = 1) AS total_verified_amount
            FROM orphan_groups g JOIN users u ON g.closed_by = u.id OR g.closed_by IS NULL AND u.id = g.closed_by -- Fallback to get nanny if needed, better to join via assignments
            LEFT JOIN nanny_group_assignments nga ON g.id = nga.group_id AND nga.end_date IS NULL
            LEFT JOIN users n ON nga.nanny_id = n.id
            WHERE g.verification_status IN ('submitted', 'approved')
            GROUP BY g.id
            ORDER BY g.submitted_at DESC";
    
    // Simplified query for reliability based on your schema:
    $sql = "SELECT g.id, g.group_name, g.verification_status, g.submitted_at, 
            (SELECT full_name FROM users WHERE id = (SELECT nanny_id FROM nanny_group_assignments WHERE group_id = g.id AND end_date IS NULL LIMIT 1)) AS nanny_name,
            (SELECT COUNT(*) FROM nanny_family_verifications v JOIN group_children gc ON v.family_id = gc.family_id WHERE gc.group_id = g.id AND v.month = ? AND v.is_orphan_verified = 1 AND v.is_mother_contact_verified = 1 AND v.is_bank_verified = 1) AS verified_families_count,
            (SELECT COALESCE(SUM(sp.monthly_amount), 0) FROM nanny_family_verifications v JOIN group_children gc ON v.family_id = gc.family_id LEFT JOIN sponsorships sp ON sp.child_id = gc.child_id AND sp.status = 'active' WHERE gc.group_id = g.id AND v.month = ? AND v.is_orphan_verified = 1 AND v.is_mother_contact_verified = 1 AND v.is_bank_verified = 1) AS total_verified_amount
            FROM orphan_groups g
            WHERE g.verification_status IN ('submitted', 'approved')
            ORDER BY g.submitted_at DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$month, $month]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * إنشاء دفعة صرف للمجموعة (للعائلات المُتحقق منها فقط)
 */
function create_group_disbursement_batch($pdo, $group_id, $month, $accountant_id) {
    $pdo->beginTransaction();
    try {
        $group = dbFetchOne("SELECT id, verification_status FROM orphan_groups WHERE id = ?", [$group_id]);
        if (!$group || $group['verification_status'] !== 'submitted') {
            throw new Exception("المجموعة غير مؤهلة لإنشاء دفعة (ليست في حالة 'submitted').");
        }

        $verified_families = dbFetchAll("SELECT gc.family_id, COALESCE(SUM(sp.monthly_amount), 0) AS amount FROM group_children gc JOIN nanny_family_verifications v ON gc.family_id = v.family_id LEFT JOIN sponsorships sp ON sp.child_id = gc.child_id AND sp.status = 'active' WHERE gc.group_id = ? AND v.month = ? AND v.is_orphan_verified = 1 AND v.is_mother_contact_verified = 1 AND v.is_bank_verified = 1 GROUP BY gc.family_id", [$group_id, $month]);

        if (empty($verified_families)) {
            throw new Exception("لا توجد عائلات مُتحقق منها بالكامل في هذه المجموعة لهذا الشهر.");
        }

        $total_amount = array_sum(array_column($verified_families, 'amount'));
        $nanny_id = dbFetchOne("SELECT nanny_id FROM nanny_group_assignments WHERE group_id = ? AND end_date IS NULL LIMIT 1", [$group_id])['nanny_id'] ?? 0;

        dbExecute("INSERT INTO monthly_disbursements (month, nanny_id, group_id, total_amount, status, expense_account_code, reviewed_by_user_id, reviewed_at, created_by, submitted_by, submitted_at) VALUES (?, ?, ?, ?, 'approved', '5100', ?, NOW(), ?, ?, NOW())", [$month, $nanny_id, $group_id, $total_amount, $accountant_id, $accountant_id, $accountant_id]);
        $batch_id = dbFetchOne("SELECT LAST_INSERT_ID() as id")['id'];

        $item_stmt = $pdo->prepare("INSERT INTO disbursement_items (disbursement_id, family_id, amount, status, is_orphan_verified, is_mother_contact_verified, is_bank_verified) VALUES (?, ?, ?, 'pending', 1, 1, 1)");
        foreach ($verified_families as $family) {
            $item_stmt->execute([$batch_id, $family['family_id'], $family['amount']]);
        }

        dbExecute("UPDATE orphan_groups SET verification_status = 'approved', approved_at = NOW() WHERE id = ?", [$group_id]);
        log_group_workflow_action($pdo, $group_id, null, $month, 'group_approved', $accountant_id, 'accountant', 'submitted', 'approved', 'تم إنشاء دفعة الصرف للعائلات المُتحقق منها');

        $pdo->commit();
        return ['success' => true, 'batch_id' => $batch_id, 'message' => 'تم إنشاء دفعة الصرف بنجاح.'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * تأكيد تحويل الدفعة ورفع إيصال التحويل البنكي
 */
function transfer_group_batch($pdo, $batch_id, $receipt_file_path, $user_id) {
    $pdo->beginTransaction();
    try {
        $batch = dbFetchOne("SELECT id, group_id, status FROM monthly_disbursements WHERE id = ?", [$batch_id]);
        if (!$batch || !in_array($batch['status'], ['approved', 'pending_approval'])) {
            throw new Exception("حالة الدفعة لا تسمح بالتحويل.");
        }

        dbExecute("UPDATE monthly_disbursements SET status = 'transferred', transferred_at = NOW(), receipt_file_path = ?, transfer_receipt_by_user_id = ?, transfer_receipt_at = NOW() WHERE id = ?", [$receipt_file_path, $user_id, $batch_id]);

        if ($batch['group_id']) {
            dbExecute("UPDATE orphan_groups SET verification_status = 'transferred', transferred_at = NOW() WHERE id = ?", [$batch['group_id']]);
            log_group_workflow_action($pdo, $batch['group_id'], null, null, 'group_transferred', $user_id, 'accountant', 'approved', 'transferred', 'تم رفع إيصال التحويل البنكي');
        }

        $pdo->commit();
        return ['success' => true, 'message' => 'تم تأكيد التحويل ورفع الإيصال بنجاح.'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * إعادة فتح مجموعة كاملة للصرف
 */
function reopen_group($pdo, $group_id, $reason, $user_id) {
    $pdo->beginTransaction();
    try {
        $group = dbFetchOne("SELECT verification_status FROM orphan_groups WHERE id = ?", [$group_id]);
        $old_status = $group['verification_status'];

        dbExecute("UPDATE orphan_groups SET verification_status = 'pending', submitted_at = NULL, approved_at = NULL WHERE id = ?", [$group_id]);
        log_group_workflow_action($pdo, $group_id, null, null, 'group_reopened', $user_id, 'accountant', $old_status, 'pending', $reason);

        $pdo->commit();
        return ['success' => true, 'message' => 'تم إعادة فتح المجموعة بنجاح.'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * إعادة فتح تحقق عائلة محددة (إلغاء التحقق)
 */
function reopen_family_verification($pdo, $family_id, $month, $reason, $user_id) {
    $pdo->beginTransaction();
    try {
        $verification = dbFetchOne("SELECT * FROM nanny_family_verifications WHERE family_id = ? AND month = ?", [$family_id, $month]);
        if (!$verification) throw new Exception("سجل التحقق غير موجود.");

        dbExecute("INSERT INTO verification_audit_log (family_id, month, nanny_id, old_orphan, old_mother, old_bank, new_orphan, new_mother, new_bank, changed_at) VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, NOW())", [$family_id, $month, $verification['nanny_id'], $verification['is_orphan_verified'], $verification['is_mother_contact_verified'], $verification['is_bank_verified']]);

        dbExecute("UPDATE nanny_family_verifications SET is_orphan_verified = 0, is_mother_contact_verified = 0, is_bank_verified = 0, verification_notes = CONCAT(IFNULL(verification_notes, ''), '\n[إعادة فتح بواسطة المحاسب: ', ?, ']') WHERE family_id = ? AND month = ?", [$reason, $family_id, $month]);

        log_group_workflow_action($pdo, null, $family_id, $month, 'family_reopened', $user_id, 'accountant', 'verified', 'pending', $reason);

        $pdo->commit();
        return ['success' => true, 'message' => 'تم إعادة فتح سجل العائلة للتعديل.'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * دالة مساعدة لتسجيل أحداث سير العمل
 */
function log_group_workflow_action($pdo, $group_id, $family_id, $month, $action_type, $actor_user_id, $actor_role, $old_status, $new_status, $reason) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO group_workflow_audit_log (group_id, family_id, month, action_type, actor_user_id, actor_role, old_status, new_status, reason, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$group_id, $family_id, $month, $action_type, $actor_user_id, $actor_role, $old_status, $new_status, $reason, $ip, $ua]);
}
?>