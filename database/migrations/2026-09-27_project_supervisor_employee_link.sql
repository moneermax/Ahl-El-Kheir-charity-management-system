-- Repair the project supervisor account created without its employee profile.
-- PS1 is user id 34; the repository snapshot contains no employees row for user 34.
-- The employee profile is created here once, using the account's existing identity data.
INSERT INTO employees
    (user_id, full_name, gender, phone, email, department_id, employee_code,
     hire_date, position, employment_type, work_mode, basic_salary, status, created_by)
SELECT
    34,
    u.full_name,
    CASE
        WHEN u.gender IN ('male', 'female') THEN u.gender
        ELSE NULL
    END,
    u.phone,
    u.email,
    8,
    CONCAT(
        'EMP-',
        LPAD(
            CAST(
                COALESCE(
                    (SELECT MAX(CAST(SUBSTRING(e.employee_code, 5) AS UNSIGNED))
                     FROM employees e
                     WHERE e.employee_code REGEXP '^EMP-[0-9]+$'),
                    0
                ) + 1 AS CHAR
            ),
            4,
            '0'
        )
    ),
    DATE(u.created_at),
    'مشرف مشروع',
    'full_time',
    'remote',
    0.00,
    'active',
    1
FROM users u
WHERE u.id = 34
  AND u.username = 'ps1'
  AND u.role_id = (SELECT id FROM roles WHERE code = 'project_supervisor')
  AND NOT EXISTS (
      SELECT 1 FROM employees e WHERE e.user_id = u.id
  );
