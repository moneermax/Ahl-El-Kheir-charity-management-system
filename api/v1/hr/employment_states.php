<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
ak_api_require_method('GET');
$user = ak_api_authenticated_user();
if (!in_array((string)($user['raw_role_code'] ?? ''), ['hr_manager', 'admin'], true)) {
    ak_api_json(403, ['ok' => false, 'error' => ['code' => 'forbidden', 'message' => 'Employment state access is restricted to HR Manager and administrator roles.']]);
}
require_once dirname(__DIR__, 3) . '/modules/hr/lib_employment.php';
try {
    $states = hrGetEmploymentStates();
    $rows = dbFetchAll(
        "SELECT e.id, e.employee_code, e.full_name, e.status,
                e.employment_state_id, e.employment_state_changed_at,
                s.code AS state_code, s.name_ar AS state_name, s.name_en AS state_name_en,
                h.effective_from AS history_from, h.reason AS history_reason
         FROM employees e
         LEFT JOIN hr_employment_states s ON s.id = e.employment_state_id
         LEFT JOIN hr_employee_state_history h ON h.employee_id = e.id AND h.effective_to IS NULL
         ORDER BY e.full_name ASC, e.id ASC"
    );
    $linked = 0;
    $currentHistory = 0;
    foreach ($rows as $row) {
        if (!empty($row['employment_state_id'])) $linked++;
        if (!empty($row['history_from'])) $currentHistory++;
    }
    ak_api_json(200, ['ok' => true, 'data' => [
        'states' => $states, 'employees' => $rows,
        'metrics' => ['defined_states' => count($states), 'linked_employees' => $linked, 'current_history_rows' => $currentHistory],
        'read_only' => true,
    ]]);
} catch (Throwable $e) {
    error_log('Mobile employment states failed: ' . $e->getMessage());
    ak_api_json(500, ['ok' => false, 'error' => ['code' => 'employment_states_failed', 'message' => 'Employment state records could not be loaded.']]);
}
