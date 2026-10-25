<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/modules/reports/report_registry.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();
$role = (string)$user['raw_role_code'];
if (!ak_report_can_access('overview', $role)) {
    ak_api_json(403, ['ok' => false, 'error' => [
        'code' => 'forbidden',
        'message' => 'General reports access is not permitted for this role.',
    ]]);
}

$stats = [
    'sponsors' => (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsors WHERE status = 'active'")['c'] ?? 0),
    'families' => (int)(dbFetchOne("SELECT COUNT(*) AS c FROM families WHERE status = 'active'")['c'] ?? 0),
    'active_sponsorships' => (int)(dbFetchOne("SELECT COUNT(*) AS c FROM sponsorships WHERE status = 'active'")['c'] ?? 0),
    'monthly_commitment' => (float)(dbFetchOne("SELECT COALESCE(SUM(monthly_amount), 0) AS total FROM sponsorships WHERE status = 'active'")['total'] ?? 0),
    'orphaned_families' => (int)(dbFetchOne(
        "SELECT COUNT(*) AS c FROM families f
         WHERE f.status IN ('active','pending')
           AND EXISTS (SELECT 1 FROM family_children fc WHERE fc.family_id = f.id AND fc.is_active = 1)
           AND NOT EXISTS (SELECT 1 FROM sponsorships sp JOIN family_children fc ON sp.child_id = fc.id
                           WHERE fc.family_id = f.id AND sp.status = 'active')"
    )['c'] ?? 0),
];
$catalog = [];
foreach (ak_report_allowed_catalog($role) as $key => $report) {
    if ($key === 'overview') {
        continue;
    }
    $catalog[] = [
        'key' => (string)$key,
        'label' => (string)$report['label'],
        'description' => (string)$report['description'],
        'native' => $key === 'orphaned',
    ];
}
ak_api_json(200, ['ok' => true, 'data' => ['stats' => $stats, 'reports' => $catalog]]);
