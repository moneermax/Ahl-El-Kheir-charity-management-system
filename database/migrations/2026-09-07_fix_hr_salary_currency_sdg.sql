-- Ahl El Kheir Charity Management System
-- HR currency normalization: SDG is the system-wide HR currency.
--
-- This migration fixes salary-history synchronization triggers that were
-- created with the old EGP currency and normalizes existing HR salary-history
-- currency labels to the current system currency.

SET NAMES utf8mb4;

UPDATE hr_employee_salary_history
SET salary_currency = 'SDG'
WHERE salary_currency IS NULL OR salary_currency = '' OR salary_currency = 'EGP';

DROP TRIGGER IF EXISTS trg_employees_salary_history_sync_ai;
DROP TRIGGER IF EXISTS trg_employees_salary_history_sync_au;

DELIMITER $$

CREATE TRIGGER trg_employees_salary_history_sync_ai
AFTER INSERT ON employees
FOR EACH ROW
BEGIN
    IF NOT EXISTS (SELECT 1 FROM hr_employee_salary_history WHERE employee_id = NEW.id LIMIT 1) THEN
        INSERT INTO hr_employee_salary_history
            (employee_id, contract_id, effective_from, effective_to,
             basic_salary, salary_currency, pay_frequency, reason, notes)
        VALUES
            (NEW.id, NULL, COALESCE(NEW.hire_date, CURDATE()), NULL,
             COALESCE(NEW.basic_salary, 0.00), 'SDG', 'monthly', 'initial',
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

    IF COALESCE(@hr_salary_history_sync, 0) <> 1
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

        IF v_current_id IS NOT NULL
           AND EXISTS (SELECT 1 FROM hr_employee_salary_history WHERE id = v_current_id AND effective_from = CURDATE()) THEN
            UPDATE hr_employee_salary_history
            SET basic_salary = NEW.basic_salary,
                salary_currency = 'SDG',
                notes = 'Synchronized from employee record'
            WHERE id = v_current_id;
        ELSE
            IF v_current_id IS NOT NULL THEN
                UPDATE hr_employee_salary_history
                SET effective_to = DATE_SUB(CURDATE(), INTERVAL 1 DAY),
                    salary_currency = 'SDG'
                WHERE id = v_current_id;
            END IF;

            SET v_target_to = CASE
                WHEN v_future_from IS NOT NULL THEN DATE_SUB(v_future_from, INTERVAL 1 DAY)
                ELSE NULL
            END;

            INSERT INTO hr_employee_salary_history
                (employee_id, contract_id, effective_from, effective_to,
                 basic_salary, salary_currency, pay_frequency, reason, notes)
            VALUES
                (NEW.id, NULL, CURDATE(), v_target_to,
                 COALESCE(NEW.basic_salary, 0.00), 'SDG', 'monthly',
                 'adjustment', 'Synchronized from employee record');
        END IF;
    END IF;
END$$

DELIMITER ;

SELECT TRIGGER_NAME, EVENT_MANIPULATION, ACTION_TIMING
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND TRIGGER_NAME IN ('trg_employees_salary_history_sync_ai','trg_employees_salary_history_sync_au')
ORDER BY TRIGGER_NAME;
