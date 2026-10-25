<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/v1/_bootstrap.php';

$cases = [
    'admin' => 'admin',
    'sudo' => 'admin',
    'super_admin' => 'admin',
    'general_manager' => 'general_manager',
    'gm' => 'general_manager',
    'vice_general_manager' => 'vice_general_manager',
    'vgm' => 'vice_general_manager',
    'financial_manager' => 'financial_manager',
    'fm' => 'financial_manager',
    'finance' => 'financial_manager',
    // Staff has role-specific dashboard differences and is deliberately not aliased here.
    'staff' => 'staff',
    'administration' => 'administration',
    'accountant_staff' => 'accountant_staff',
    'projects_manager' => 'projects_manager',
];

foreach ($cases as $input => $expected) {
    $actual = ak_api_normalize_role_code($input);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL | {$input} expected {$expected}, got {$actual}\n");
        exit(1);
    }
}

echo 'PASS | mobile API role normalization | ' . count($cases) . " cases\n";
