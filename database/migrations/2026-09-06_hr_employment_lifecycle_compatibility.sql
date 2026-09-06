-- HR employment lifecycle compatibility bridge
-- Keeps legacy employees.status writes synchronized with the canonical
-- employment state/history model while older HR pages are being migrated.

DELIMITER $$

DROP TRIGGER IF EXISTS trg_employees_employment_state_bi$$
CREATE TRIGGER trg_employees_employment_state_bi
BEFORE INSERT ON employees
FOR EACH ROW
BEGIN
    DECLARE v_state_id INT DEFAULT NULL;

    IF NEW.employment_state_id IS NULL OR NEW.employment_state_id = 0 THEN
        SELECT id INTO v_state_id
        FROM hr_employment_states
        WHERE code = CASE
            WHEN NEW.status = 'suspended' THEN 'suspended'
            WHEN NEW.status = 'terminated' THEN 'terminated'
            ELSE 'active'
        END
          AND is_active = 1
        LIMIT 1;

        SET NEW.employment_state_id = v_state_id;
    END IF;

    IF NEW.employment_state_changed_at IS NULL THEN
        SET NEW.employment_state_changed_at = NOW();
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_employees_employment_state_bu$$
CREATE TRIGGER trg_employees_employment_state_bu
BEFORE UPDATE ON employees
FOR EACH ROW
BEGIN
    DECLARE v_state_id INT DEFAULT NULL;

    -- Legacy pages still write employees.status. If the canonical state was
    -- not explicitly changed in the same UPDATE, translate that legacy value
    -- into the canonical employment state.
    IF NOT (NEW.employment_state_id <=> OLD.employment_state_id)
       AND NEW.employment_state_id IS NOT NULL THEN
        -- Explicit canonical state change: let the HR service control it.
        SET NEW.employment_state_changed_at = COALESCE(NEW.employment_state_changed_at, NOW());
    ELSEIF NOT (NEW.status <=> OLD.status) THEN
        SELECT id INTO v_state_id
        FROM hr_employment_states
        WHERE code = CASE
            WHEN NEW.status = 'suspended' THEN 'suspended'
            WHEN NEW.status = 'terminated' THEN 'terminated'
            ELSE 'active'
        END
          AND is_active = 1
        LIMIT 1;

        SET NEW.employment_state_id = v_state_id;
        SET NEW.employment_state_changed_at = NOW();
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_employees_employment_state_ai$$
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
        'Initial employee creation (legacy compatibility)',
        NEW.created_by
    WHERE NEW.employment_state_id IS NOT NULL
      AND NOT EXISTS (
          SELECT 1
          FROM hr_employee_state_history h
          WHERE h.employee_id = NEW.id
      );
END$$

DROP TRIGGER IF EXISTS trg_employees_employment_state_au$$
CREATE TRIGGER trg_employees_employment_state_au
AFTER UPDATE ON employees
FOR EACH ROW
BEGIN
    -- When a legacy status write caused a canonical state change, create the
    -- missing history row. hrSetEmploymentState() already creates its own
    -- history row, so this guard prevents duplication there.
    IF NOT (NEW.employment_state_id <=> OLD.employment_state_id)
       AND NEW.employment_state_id IS NOT NULL
       AND NOT EXISTS (
           SELECT 1
           FROM hr_employee_state_history h
           WHERE h.employee_id = NEW.id
             AND h.employment_state_id = NEW.employment_state_id
             AND h.effective_to IS NULL
       ) THEN
        UPDATE hr_employee_state_history
        SET effective_to = COALESCE(NEW.employment_state_changed_at, NOW())
        WHERE employee_id = NEW.id
          AND effective_to IS NULL;

        INSERT INTO hr_employee_state_history
            (employee_id, employment_state_id, effective_from, effective_to, reason, changed_by)
        VALUES
            (NEW.id,
             NEW.employment_state_id,
             COALESCE(NEW.employment_state_changed_at, NOW()),
             NULL,
             'Legacy employees.status lifecycle change',
             NULL);
    END IF;
END$$

DELIMITER ;

-- Repair any employee rows that may have been changed through legacy status
-- after the original foundation migration.
UPDATE employees e
JOIN hr_employment_states s
  ON s.code = CASE
      WHEN e.status = 'suspended' THEN 'suspended'
      WHEN e.status = 'terminated' THEN 'terminated'
      ELSE 'active'
  END
 AND s.is_active = 1
SET e.employment_state_id = s.id,
    e.employment_state_changed_at = COALESCE(e.employment_state_changed_at, NOW())
WHERE e.employment_state_id IS NULL;

INSERT INTO hr_employee_state_history
    (employee_id, employment_state_id, effective_from, effective_to, reason, changed_by)
SELECT
    e.id,
    e.employment_state_id,
    COALESCE(e.employment_state_changed_at, NOW()),
    NULL,
    'Employment state compatibility backfill',
    e.created_by
FROM employees e
WHERE e.employment_state_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM hr_employee_state_history h WHERE h.employee_id = e.id
  );
