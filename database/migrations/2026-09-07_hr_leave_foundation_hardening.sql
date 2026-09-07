-- Ahl El Kheir Charity Management System
-- HR Foundation - Leave Management Hardening
--
-- Purpose:
--   Protect the Leave -> Attendance boundary at database level.
--   The existing leaves.php workflow remains unchanged.
--
-- Rules enforced here:
--   1. Leave dates must be valid.
--   2. Leave can only belong to an employee in a working employment state.
--   3. A pending/approved leave may not overlap another pending/approved leave
--      for the same employee.
--
-- Rejected requests remain allowed so a new corrected request can be submitted.

SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_leaves_validate_insert;
DROP TRIGGER IF EXISTS trg_leaves_validate_update;

DELIMITER $$

CREATE TRIGGER trg_leaves_validate_insert
BEFORE INSERT ON leaves
FOR EACH ROW
BEGIN
    DECLARE v_state_category VARCHAR(30) DEFAULT NULL;

    IF NEW.start_date IS NULL OR NEW.end_date IS NULL OR NEW.end_date < NEW.start_date THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'تواريخ الإجازة غير صالحة.';
    END IF;

    SELECT s.category
      INTO v_state_category
      FROM employees e
      JOIN hr_employment_states s ON s.id = e.employment_state_id
     WHERE e.id = NEW.employee_id
     LIMIT 1;

    IF v_state_category IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'لا يمكن إنشاء طلب إجازة لموظف بدون حالة توظيف صالحة.';
    END IF;

    IF v_state_category <> 'working' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'لا يمكن إنشاء طلب إجازة لموظف خارج حالات العمل.';
    END IF;

    IF NEW.status IN ('pending', 'manager_approved', 'hr_approved')
       AND EXISTS (
           SELECT 1
             FROM leaves l
            WHERE l.employee_id = NEW.employee_id
              AND l.status IN ('pending', 'manager_approved', 'hr_approved')
              AND NEW.start_date <= l.end_date
              AND NEW.end_date >= l.start_date
       ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'يوجد طلب إجازة آخر متداخل مع الفترة المحددة.';
    END IF;
END$$

CREATE TRIGGER trg_leaves_validate_update
BEFORE UPDATE ON leaves
FOR EACH ROW
BEGIN
    DECLARE v_state_category VARCHAR(30) DEFAULT NULL;

    IF NEW.start_date IS NULL OR NEW.end_date IS NULL OR NEW.end_date < NEW.start_date THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'تواريخ الإجازة غير صالحة.';
    END IF;

    SELECT s.category
      INTO v_state_category
      FROM employees e
      JOIN hr_employment_states s ON s.id = e.employment_state_id
     WHERE e.id = NEW.employee_id
     LIMIT 1;

    IF v_state_category IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'لا يمكن حفظ طلب إجازة لموظف بدون حالة توظيف صالحة.';
    END IF;

    IF v_state_category <> 'working' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'لا يمكن حفظ طلب إجازة لموظف خارج حالات العمل.';
    END IF;

    IF NEW.status IN ('pending', 'manager_approved', 'hr_approved')
       AND EXISTS (
           SELECT 1
             FROM leaves l
            WHERE l.id <> NEW.id
              AND l.employee_id = NEW.employee_id
              AND l.status IN ('pending', 'manager_approved', 'hr_approved')
              AND NEW.start_date <= l.end_date
              AND NEW.end_date >= l.start_date
       ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'يوجد طلب إجازة آخر متداخل مع الفترة المحددة.';
    END IF;
END$$

DELIMITER ;

SELECT
    (SELECT COUNT(*) FROM information_schema.TRIGGERS
      WHERE TRIGGER_SCHEMA = DATABASE()
        AND TRIGGER_NAME = 'trg_leaves_validate_insert') AS leave_insert_trigger,
    (SELECT COUNT(*) FROM information_schema.TRIGGERS
      WHERE TRIGGER_SCHEMA = DATABASE()
        AND TRIGGER_NAME = 'trg_leaves_validate_update') AS leave_update_trigger;
