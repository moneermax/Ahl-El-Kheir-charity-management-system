-- Ahl El Kheir Charity Management System
-- HR salary-history synchronization trigger correction
--
-- Purpose:
--   Replace any previously-created salary synchronization trigger with the
--   current implementation. The application helper intentionally creates a
--   trigger only when it does not exist, so an older trigger definition could
--   remain active after the PHP implementation was corrected.
--
-- This migration is safe for existing salary-history data: it changes only
-- the trigger definition and does not modify employee or salary rows.

SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_employees_salary_history_sync_ai;
DROP TRIGGER IF EXISTS trg_employees_salary_history_sync_au;

DELIMITER $$

CREATE TRIGGER trg_employees_salary_history_sync_ai
AFTER INSERT ON employees
FOR EACH ROW
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM hr_employee_salary_history
        WHERE employee_id = NEW.id
        LIMIT 1
    ) THEN
        INSERT INTO hr_employee_salary_history
            (employee_id, contract_id, effective_from, effective_to,
             basic_salary, salary_currency, pay_frequency, reason, notes)
        VALUES
            (NEW.id, NULL, COALESCE(NEW.hire_date, CURDATE()), NULL,
             COALESCE(NEW.basic_salary, 0.00), 'EGP', 'monthly', 'initial',
             'Initial salary history created from employee record');
    END IF;
END$$

CREATE TRIGGER trg_employees_salary_history_sync_au
AFTER UPDATE ON employees
FOR EACH ROW
BEGIN
    DECLARE v_future_from DATE DEFAULT NULL;
    DECLARE v_current_id BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_target_to DATE DEFAULT NULL;

    IF NOT (@hr_salary_history_sync = 1)
       AND NOT (OLD.basic_salary <=> NEW.basic_salary) THEN

        SELECT id INTO v_current_id
        FROM hr_employee_salary_history
        WHERE employee_id = NEW.id
          AND effective_from <= CURDATE()
          AND (effective_to IS NULL OR effective_to >= CURDATE())
        ORDER BY effective_from DESC, id DESC
        LIMIT 1;

        SELECT MIN(effective_from) INTO v_future_from
        FROM hr_employee_salary_history
        WHERE employee_id = NEW.id
          AND effective_from > CURDATE();

        -- If the current salary row starts today, update it in place. This is
        -- the required behavior for same-day edits made by the legacy employee
        -- screen.
        IF v_current_id IS NOT NULL
           AND EXISTS (
               SELECT 1
               FROM hr_employee_salary_history
               WHERE id = v_current_id
                 AND effective_from = CURDATE()
           ) THEN
            UPDATE hr_employee_salary_history
            SET basic_salary = NEW.basic_salary,
                notes = 'Synchronized from employee record'
            WHERE id = v_current_id;
        ELSE
            -- Otherwise close the currently-effective segment yesterday and
            -- create today's compatibility adjustment segment.
            IF v_current_id IS NOT NULL THEN
                UPDATE hr_employee_salary_history
                SET effective_to = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                WHERE id = v_current_id;
            END IF;

            SET v_target_to = CASE
                WHEN v_future_from IS NOT NULL
                    THEN DATE_SUB(v_future_from, INTERVAL 1 DAY)
                ELSE NULL
            END;

            INSERT INTO hr_employee_salary_history
                (employee_id, contract_id, effective_from, effective_to,
                 basic_salary, salary_currency, pay_frequency, reason, notes)
            VALUES
                (NEW.id, NULL, CURDATE(), v_target_to,
                 COALESCE(NEW.basic_salary, 0.00), 'EGP', 'monthly',
                 'adjustment', 'Synchronized from employee record');
        END IF;
    END IF;
END$$

DELIMITER ;

-- Verification: these should return exactly two rows.
SELECT TRIGGER_NAME, EVENT_MANIPULATION, ACTION_TIMING
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND TRIGGER_NAME IN (
      'trg_employees_salary_history_sync_ai',
      'trg_employees_salary_history_sync_au'
  )
ORDER BY TRIGGER_NAME;
