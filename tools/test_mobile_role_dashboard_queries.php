<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/v1/dashboard/overview_service.php';

$roles = [
    'general_manager',
    'vice_general_manager',
    'financial_manager',
    'accountant',
    'accountant_staff',
    'supervisor',
    'nanny',
    'administration',
    'staff',
    'social_media',
    'hr_manager',
    'hr_staff',
    'projects_manager',
    'project_supervisor',
];
$fixtureUserId = (int)(dbFetchOne('SELECT id FROM users ORDER BY id LIMIT 1')['id'] ?? 1);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

foreach ($roles as $role) {
    try {
        $metrics = ak_mobile_dashboard_metrics($role, $fixtureUserId);
        $assert(count($metrics) > 0, $role . ': no dashboard metrics returned.');
        if ($role === 'general_manager') {
            $pendingFamilies = array_values(array_filter(
                $metrics,
                static fn(array $metric): bool => $metric['key'] === 'pending_families'
            ));
            $assert(count($pendingFamilies) === 1, 'general_manager: pending_families metric is missing or duplicated.');
            $assert(is_numeric($pendingFamilies[0]['value']), 'general_manager: pending_families metric value is not numeric.');
        }
        foreach ($metrics as $metric) {
            $assert(isset($metric['key'], $metric['label_ar'], $metric['label_en']) && array_key_exists('value', $metric),
                $role . ': metric response is missing a required field.');
        }
        echo 'PASS | ' . $role . ' | metrics=' . count($metrics) . PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR, 'FAIL | ' . $role . ' | ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo 'PASS | mobile role dashboard SQL | ' . count($roles) . ' roles' . PHP_EOL;
