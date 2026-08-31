<<<<<<< HEAD
<?php
/**
 * validation_rules.php
 * Business rules that must ALWAYS be enforced
 */

/**
 * Rule 1: A family can only be submitted if ALL three checks are verified
 */
function validate_family_submission($family_id, $month, $nanny_id) {
    $status = get_latest_verification_status($family_id, $month, $nanny_id);
    
    $errors = [];
    
    if ($status['is_orphan'] !== 1) {
        $errors[] = 'توثيق اليتيم غير مكتمل';
    }
    if ($status['is_mother'] !== 1) {
        $errors[] = 'توثيق تواصل الأم غير مكتمل';
    }
    if ($status['is_bank'] !== 1) {
        $errors[] = 'توثيق الحساب البنكي غير مكتمل';
    }
    
    return $errors; // Empty array = valid
}

/**
 * Rule 2: Log every verification change for audit trail
 */
function log_verification_change($family_id, $month, $nanny_id, $old_status, $new_status) {
    try {
        dbExecute("
            INSERT INTO verification_audit_log 
            (family_id, month, nanny_id, old_orphan, old_mother, old_bank, 
             new_orphan, new_mother, new_bank, changed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ", [
            $family_id, $month, $nanny_id,
            $old_status['is_orphan'], $old_status['is_mother'], $old_status['is_bank'],
            $new_status['is_orphan'], $new_status['is_mother'], $new_status['is_bank']
        ]);
    } catch (Throwable $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
=======
<?php
/**
 * validation_rules.php
 * Business rules that must ALWAYS be enforced
 */

/**
 * Rule 1: A family can only be submitted if ALL three checks are verified
 */
function validate_family_submission($family_id, $month, $nanny_id) {
    $status = get_latest_verification_status($family_id, $month, $nanny_id);
    
    $errors = [];
    
    if ($status['is_orphan'] !== 1) {
        $errors[] = 'توثيق اليتيم غير مكتمل';
    }
    if ($status['is_mother'] !== 1) {
        $errors[] = 'توثيق تواصل الأم غير مكتمل';
    }
    if ($status['is_bank'] !== 1) {
        $errors[] = 'توثيق الحساب البنكي غير مكتمل';
    }
    
    return $errors; // Empty array = valid
}

/**
 * Rule 2: Log every verification change for audit trail
 */
function log_verification_change($family_id, $month, $nanny_id, $old_status, $new_status) {
    try {
        dbExecute("
            INSERT INTO verification_audit_log 
            (family_id, month, nanny_id, old_orphan, old_mother, old_bank, 
             new_orphan, new_mother, new_bank, changed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ", [
            $family_id, $month, $nanny_id,
            $old_status['is_orphan'], $old_status['is_mother'], $old_status['is_bank'],
            $new_status['is_orphan'], $new_status['is_mother'], $new_status['is_bank']
        ]);
    } catch (Throwable $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
>>>>>>> 7f4282655b6a978af854bb06ec52ae2d69fddbef
}