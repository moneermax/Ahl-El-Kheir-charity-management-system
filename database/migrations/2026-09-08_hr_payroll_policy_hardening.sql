-- Ahl El Kheir Charity Management System
-- HR Payroll Policy Foundation - Stage 1 hardening
-- Database-level validation and immutability safeguards.
-- No payroll calculations are performed by this migration.

SET NAMES utf8mb4;

DROP TRIGGER IF EXISTS trg_hr_payroll_policy_validate_insert;
DELIMITER $$
CREATE TRIGGER trg_hr_payroll_policy_validate_insert
BEFORE INSERT ON hr_payroll_policy_versions
FOR EACH ROW
BEGIN
    IF NEW.version_no < 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'رقم إصدار سياسة الرواتب يجب أن يكون أكبر من صفر.';
    END IF;

    IF NEW.effective_from <= CURDATE() THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'تاريخ سريان سياسة الرواتب يجب أن يكون مستقبلياً.';
    END IF;

    IF NEW.absence_enabled NOT IN (0, 1)
       OR NEW.unpaid_leave_enabled NOT IN (0, 1)
       OR NEW.late_enabled NOT IN (0, 1)
       OR NEW.early_departure_enabled NOT IN (0, 1)
       OR NEW.overtime_enabled NOT IN (0, 1) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'قيم تفعيل سياسات الرواتب غير صالحة.';
    END IF;

    IF NEW.absence_deduction_percent < 0 OR NEW.absence_deduction_percent > 100
       OR NEW.unpaid_leave_deduction_percent < 0 OR NEW.unpaid_leave_deduction_percent > 100
       OR NEW.paid_leave_deduction_percent < 0 OR NEW.paid_leave_deduction_percent > 100 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'نسب خصم سياسات الرواتب يجب أن تكون بين 0 و100.';
    END IF;

    IF NEW.paid_leave_deduction_percent <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'خصم الإجازة المدفوعة يجب أن يساوي 0 في الإصدار الأول.';
    END IF;

    IF NEW.overtime_multiplier <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'معامل العمل الإضافي يجب أن يكون أكبر من صفر.';
    END IF;

    IF NEW.daily_deduction_method <> 'monthly_salary_div_30' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'طريقة الخصم اليومية غير مدعومة في الإصدار الأول.';
    END IF;

    IF NEW.rounding_decimals < 0 OR NEW.rounding_decimals > 4 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'عدد المنازل العشرية يجب أن يكون بين 0 و4.';
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_hr_payroll_policy_validate_update;
DELIMITER $$
CREATE TRIGGER trg_hr_payroll_policy_validate_update
BEFORE UPDATE ON hr_payroll_policy_versions
FOR EACH ROW
BEGIN
    -- Once effective, a policy is immutable. A future policy cannot be
    -- edited into an already-effective date either.
    IF OLD.effective_from <= CURDATE() OR NEW.effective_from <= CURDATE() THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'إصدارات سياسة الرواتب السارية أو التي ستصبح سارية لا يمكن تعديلها. أنشئ إصداراً جديداً.';
    END IF;

    IF NEW.version_no < 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'رقم إصدار سياسة الرواتب يجب أن يكون أكبر من صفر.';
    END IF;

    IF NEW.absence_enabled NOT IN (0, 1)
       OR NEW.unpaid_leave_enabled NOT IN (0, 1)
       OR NEW.late_enabled NOT IN (0, 1)
       OR NEW.early_departure_enabled NOT IN (0, 1)
       OR NEW.overtime_enabled NOT IN (0, 1) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'قيم تفعيل سياسات الرواتب غير صالحة.';
    END IF;

    IF NEW.absence_deduction_percent < 0 OR NEW.absence_deduction_percent > 100
       OR NEW.unpaid_leave_deduction_percent < 0 OR NEW.unpaid_leave_deduction_percent > 100
       OR NEW.paid_leave_deduction_percent < 0 OR NEW.paid_leave_deduction_percent > 100 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'نسب خصم سياسات الرواتب يجب أن تكون بين 0 و100.';
    END IF;

    IF NEW.paid_leave_deduction_percent <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'خصم الإجازة المدفوعة يجب أن يساوي 0 في الإصدار الأول.';
    END IF;

    IF NEW.overtime_multiplier <= 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'معامل العمل الإضافي يجب أن يكون أكبر من صفر.';
    END IF;

    IF NEW.daily_deduction_method <> 'monthly_salary_div_30' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'طريقة الخصم اليومية غير مدعومة في الإصدار الأول.';
    END IF;

    IF NEW.rounding_decimals < 0 OR NEW.rounding_decimals > 4 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'عدد المنازل العشرية يجب أن يكون بين 0 و4.';
    END IF;
END$$
DELIMITER ;

SELECT
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    EVENT_OBJECT_TABLE
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND EVENT_OBJECT_TABLE = 'hr_payroll_policy_versions'
ORDER BY TRIGGER_NAME;
