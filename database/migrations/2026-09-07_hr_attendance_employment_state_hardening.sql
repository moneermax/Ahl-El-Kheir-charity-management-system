-- Ahl El Kheir Charity Management System
-- HR Attendance - Employment State Hardening
--
-- Attendance is only valid for employees whose canonical employment state
-- belongs to the working category on the attendance date.
-- The existing employees.status column remains for backward compatibility;
-- employment-state history is the authoritative source for this rule.

SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_attendance_employment_state_bi;
DROP TRIGGER IF EXISTS trg_attendance_employment_state_bu;

DELIMITER $$

CREATE TRIGGER trg_attendance_employment_state_bi
BEFORE INSERT ON attendance
FOR EACH ROW
BEGIN
    DECLARE v_category VARCHAR(32) DEFAULT NULL;

    SELECT s.category
      INTO v_category
      FROM hr_employee_state_history h
      INNER JOIN hr_employment_states s
              ON s.id = h.employment_state_id
     WHERE h.employee_id = NEW.employee_id
       AND h.effective_from <= CONCAT(NEW.date, ' 23:59:59')
       AND (h.effective_to IS NULL OR h.effective_to >= CONCAT(NEW.date, ' 00:00:00'))
     ORDER BY h.effective_from DESC, h.id DESC
     LIMIT 1;

    IF v_category IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'لا يمكن تسجيل الحضور: لا توجد حالة توظيف معتمدة للموظف في هذا التاريخ.';
    END IF;

    IF v_category <> 'working' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'لا يمكن تسجيل الحضور: حالة توظيف الموظف لا تسمح بتسجيل الحضور في هذا التاريخ.';
    END IF;
END$$

CREATE TRIGGER trg_attendance_employment_state_bu
BEFORE UPDATE ON attendance
FOR EACH ROW
BEGIN
    DECLARE v_category VARCHAR(32) DEFAULT NULL;

    SELECT s.category
      INTO v_category
      FROM hr_employee_state_history h
      INNER JOIN hr_employment_states s
              ON s.id = h.employment_state_id
     WHERE h.employee_id = NEW.employee_id
       AND h.effective_from <= CONCAT(NEW.date, ' 23:59:59')
       AND (h.effective_to IS NULL OR h.effective_to >= CONCAT(NEW.date, ' 00:00:00'))
     ORDER BY h.effective_from DESC, h.id DESC
     LIMIT 1;

    IF v_category IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'لا يمكن تعديل الحضور: لا توجد حالة توظيف معتمدة للموظف في هذا التاريخ.';
    END IF;

    IF v_category <> 'working' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'لا يمكن تعديل الحضور: حالة توظيف الموظف لا تسمح بتسجيل الحضور في هذا التاريخ.';
    END IF;
END$$

DELIMITER ;

SELECT
    (SELECT COUNT(*) FROM information_schema.TRIGGERS
      WHERE TRIGGER_SCHEMA = DATABASE()
        AND TRIGGER_NAME = 'trg_attendance_employment_state_bi') AS attendance_insert_trigger,
    (SELECT COUNT(*) FROM information_schema.TRIGGERS
      WHERE TRIGGER_SCHEMA = DATABASE()
        AND TRIGGER_NAME = 'trg_attendance_employment_state_bu') AS attendance_update_trigger;
