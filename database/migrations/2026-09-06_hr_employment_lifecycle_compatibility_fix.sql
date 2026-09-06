-- HR employment lifecycle compatibility fix
-- Replaces the previous compatibility trigger set with a simpler bridge.

DELIMITER $$

DROP TRIGGER IF EXISTS trg_employees_employment_state_bi$$
DROP TRIGGER IF EXISTS trg_employees_employment_state_bu$$
DROP TRIGGER IF EXISTS trg_employees_employment_state_ai$$
DROP TRIGGER IF EXISTS trg_employees_employment_state_au$$

CREATE TRIGGER trg_employees_employment_state_bi
BEFORE INSERT ON employees
FOR EACH ROW
BEGIN
    DECLARE v_state_id INT DEFAULT NULL;

    SELECT id INTO v_state_id
    FROM hr_employment_states
    WHERE code = CASE
        WHEN NEW.status = 'suspended' THEN 'suspended'
        WHEN NEW.status = 'terminated' THEN 'terminated'
        WHEN NEW.status = 'retired' THEN 'retired'
        WHEN NEW.status = 'resigned' THEN 'resigned'
        WHEN NEW.status = 'probation' THEN 'probation'
        ELSE 'active'
    END
      AND is_active = 1
    LIMIT 1;

    IF NEW.employment_state_id IS NULL OR NEW.employment_state_id = 0 THEN
        SET NEW.employment_state_id = v_state_id;
    END IF;

    IF NEW.employment_state_changed_at IS NULL THEN
        SET NEW.employment_state_changed_at = NOW();
    END IF;
END$$

CREATE TRIGGER trg_employees_employment_state_bu
BEFORE UPDATE ON employees
FOR EACH ROW
BEGIN
    DECLARE v_state_id INT DEFAULT NULL;

    IF (NEW.employment_state_id <=> OLD.employment_state_id)
       AND NOT (NEW.status <=> OLD.status) THEN

        SELECT id INTO v_state_id
        FROM hr_employment_states
        WHERE code = CASE
            WHEN NEW.status = 'suspended' THEN 'suspended'
            WHEN NEW.status = 'terminated' THEN 'terminated'
            WHEN NEW.status = 'retired' THEN 'retired'
            WHEN NEW.status = 'resigned' THEN 'resigned'
            WHEN NEW.status = 'probation' THEN 'probation'
            ELSE 'active'
        END
          AND is_active = 1
        LIMIT 1;

        IF v_state_id IS NOT NULL AND NOT (v_state_id <=> OLD.employment_state_id) THEN
            UPDATE hr_employee_state_history
            SET effective_to = NOW()
            WHERE employee_id = OLD.id
              AND effective_to IS NULL;

            INSERT INTO hr_employee_state_history
                (employee_id, employment_state_id, effective_from, effective_to, reason, changed_by)
            VALUES
                (OLD.id, v_state_id, NOW(), NULL,
                 'Legacy employees.status lifecycle change', NULL);

            SET NEW.employment_state_id = v_state_id;
            SET NEW.employment_state_changed_at = NOW();
        END IF;
    END IF;

    IF NOT (NEW.employment_state_id <=> OLD.employment_state_id)
       AND NEW.employment_state_id IS NOT NULL
       AND (NEW.employment_state_changed_at <=> OLD.employment_state_changed_at) THEN
        SET NEW.employment_state_changed_at = NOW();
    END IF;
END$$

CREATE TRIGGER trg_employees_employment_state_ai
AFTER INSERT ON employees
FOR EACH ROW
BEGIN
    INSERT INTO hr_employee_state_history
        (employee_id, employment_state_id, effective_from, effective_to, reason, changed_by)
    SELECT
        NEW.id,
        NEW.employment_state_id,
        COALESCE(NEW.employment_state_changed_at, NOW()),
        NULL,
        'Initial employee creation',
        NEW.created_by
    WHERE NEW.employment_state_id IS NOT NULL
      AND NOT EXISTS (
          SELECT 1 FROM hr_employee_state_history h
          WHERE h.employee_id = NEW.id
      );
END$$

DELIMITER ;

-- Repair existing employees whose legacy status and canonical state disagree.
UPDATE employees e
JOIN hr_employment_states s
  ON s.code = CASE
      WHEN e.status = 'suspended' THEN 'suspended'
      WHEN e.status = 'terminated' THEN 'terminated'
      WHEN e.status = 'retired' THEN 'retired'
      WHEN e.status = 'resigned' THEN 'resigned'
      WHEN e.status = 'probation' THEN 'probation'
      ELSE 'active'
  END
 AND s.is_active = 1
SET e.employment_state_id = s.id,
    e.employment_state_changed_at = COALESCE(e.employment_state_changed_at, NOW())
WHERE e.employment_state_id IS NULL
   OR e.employment_state_id <> s.id;

-- Ensure each employee has an open history row for the current state.
UPDATE hr_employee_state_history h
JOIN employees e ON e.id = h.employee_id
SET h.effective_to = COALESCE(e.employment_state_changed_at, NOW())
WHERE h.effective_to IS NULL
  AND h.employment_state_id <> e.employment_state_id;

INSERT INTO hr_employee_state_history
    (employee_id, employment_state_id, effective_from, effective_to, reason, changed_by)
SELECT
    e.id,
    e.employment_state_id,
    COALESCE(e.employment_state_changed_at, NOW()),
    NULL,
    'Employment state compatibility repair',
    e.created_by
FROM employees e
WHERE e.employment_state_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM hr_employee_state_history h
      WHERE h.employee_id = e.id
        AND h.employment_state_id = e.employment_state_id
        AND h.effective_to IS NULL
  );
