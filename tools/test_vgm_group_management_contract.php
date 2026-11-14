<?php
declare(strict_types=1);

/**
 * Static contract checks for the VGM monthly group-opening and safe archive
 * boundary. This is not a substitute for local Apache/database acceptance.
 */
$root = dirname(__DIR__);
$files = [
    'helper' => $root . '/modules/deputy_gm/lib_group_management.php',
    'web' => $root . '/modules/deputy_gm/groups.php',
    'api' => $root . '/api/v1/deputy_gm/groups.php',
    'notifications' => $root . '/assets/js/notifications.js',
    'app' => $root . '/assets/js/app.js',
];
foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "FAIL | missing {$name} source\n");
        exit(1);
    }
}
$src = [];
foreach ($files as $name => $path) $src[$name] = (string)file_get_contents($path);
$checks = [
    'opening requires active group' => str_contains($src['helper'], "['status'] !== 'active'"),
    'opening requires current members' => str_contains($src['helper'], 'left_date IS NULL'),
    'opening requires active assigned nanny' => str_contains($src['helper'], "r.code = 'nanny'") && str_contains($src['helper'], 'u.is_active = 1'),
    'opening blocks an existing current-month batch' => str_contains($src['helper'], 'if ($currentBatch)'),
    'later opening requires closed previous cycle' => str_contains($src['helper'], '$oldStatus !== \'closed\''),
    'older batches must be terminal' => str_contains($src['helper'], "['received', 'returned', 'cancelled', 'voided']"),
    'latest prior batch must have completed Nanny closure' => str_contains($src['helper'], "['received', 'returned']"),
    'opening writes required audit evidence' => str_contains($src['helper'], 'VGM_OPEN_MONTHLY_CYCLE') && str_contains($src['helper'], 'INSERT INTO audit_log'),
    'notification happens after commit' => strpos(substr($src['helper'], strpos($src['helper'], 'function ak_vgm_open_monthly_cycles')), '$pdo->commit();') < strpos(substr($src['helper'], strpos($src['helper'], 'function ak_vgm_open_monthly_cycles')), 'ak_transaction_review_notify_event('),
    'web supports individual/bulk selected groups' => str_contains($src['web'], 'name="group_ids[]"') && str_contains($src['web'], "ak_vgm_open_monthly_cycles"),
    'mobile API supports monthly opening' => str_contains($src['api'], "'open_monthly_cycle'") && str_contains($src['api'], 'ak_vgm_open_monthly_cycles'),
    'mobile API rejects an opening with no eligible groups' => str_contains($src['api'], 'no_groups_opened'),
    'used groups are archived, not physically deleted' => str_contains($src['helper'], "SET status = 'archived', is_active = 0") && str_contains($src['helper'], 'UPDATE nanny_group_assignments SET end_date'),
    'archive blocks unfinished disbursements' => str_contains($src['helper'], "status NOT IN ('received', 'returned', 'cancelled', 'voided')"),
    'archive blocks an opened but unfinished verification cycle' => str_contains($src['helper'], 'ak_vgm_assert_group_archivable') && str_contains($src['helper'], 'VGM_OPEN_MONTHLY_CYCLE'),
    'web lifecycle archive runs inside a transaction' => str_contains($src['web'], 'in_array($action, [\'activate_group\'') && str_contains($src['web'], '$pdo->beginTransaction();') && str_contains($src['web'], '$pdo->commit();'),
    'web transfer capability remains' => str_contains($src['web'], 'elseif ($action === \'transfer_orphan\')'),
    'mobile transfer capability remains' => str_contains($src['api'], 'elseif ($action === \'transfer_orphan\')'),
    'global group-name unique index migration exists' => is_file($root . '/database/migrations/2026-11-14_vgm_unique_group_names.sql')
        && str_contains((string)file_get_contents($root . '/database/migrations/2026-11-14_vgm_unique_group_names.sql'), 'ADD UNIQUE KEY uq_orphan_groups_group_name (group_name)'),
    'web and API share group-name/description validation' => str_contains($src['helper'], 'function ak_vgm_validate_group_details')
        && str_contains($src['web'], 'ak_vgm_validate_group_details($pdo')
        && str_contains($src['api'], 'ak_vgm_validate_group_details('),
    'web rejects POST without valid CSRF token' => str_contains($src['web'], 'if (!verify_csrf())'),
    'every VGM web POST form includes CSRF token' => substr_count($src['web'], '<form') === substr_count($src['web'], 'csrf_field()'),
    'web nanny assignment validates active Nanny server-side' => str_contains($src['web'], 'ak_vgm_assert_active_nanny($pdo'),
    'shared active-Nanny validation checks role and account state' => str_contains($src['helper'], "r.code = 'nanny'") && str_contains($src['helper'], 'u.is_active = 1'),
    'web member additions lock and revalidate group inside transaction' => str_contains($src['web'], "ak_vgm_lock_operational_group(\$pdo, \$group_id)") && str_contains($src['web'], '$pdo->beginTransaction();'),
    'web nanny assignment locks and revalidates group inside transaction' => str_contains($src['web'], "ak_vgm_lock_operational_group(\$pdo, \$group_id)") && str_contains($src['web'], 'ak_vgm_assert_active_nanny($pdo'),
    'concurrent duplicate-name collision has friendly web/API handling' => str_contains($src['helper'], 'ak_vgm_is_duplicate_group_name_error')
        && str_contains($src['web'], 'اسم المجموعة مستخدم بالفعل')
        && str_contains($src['api'], "'duplicate_group_name'"),
    'VGM confirmation popups use the centralized notification handler' => str_contains($src['web'], 'data-confirm=') && str_contains($src['notifications'], "getAttribute('data-confirm')") && str_contains($src['notifications'], 'showConfirmation(message, function ()'),
    'same-page POST waits for confirmation before starting fetch' => str_contains($src['app'], "form.hasAttribute('data-confirm') && form.dataset.akConfirmBypass !== '1'") && str_contains($src['app'], 'event.stopImmediatePropagation();') && str_contains($src['app'], 'window.AKNotify.confirm') && str_contains($src['app'], 'form.requestSubmit()') && str_contains($src['notifications'], "form.dataset.akConfirmBypass = '1'"),
    'SweetAlert confirmations and toasts share a serialized popup queue' => str_contains($src['notifications'], 'var popupQueue = Promise.resolve()') && str_contains($src['notifications'], 'function enqueuePopup(showPopup)') && str_contains($src['notifications'], 'return enqueuePopup(function ()') && str_contains($src['notifications'], 'return Toast.fire(toastOptions)') && str_contains($src['notifications'], 'return window.Swal.fire({'),
    'bulk confirmation submits only through the confirmed callback' => str_contains($src['web'], 'window.AKNotify.confirm(confirmationMessage, submit') && str_contains($src['web'], "getElementById('bulkAddForm').requestSubmit()"),
    'empty bulk selection uses the correct toast argument order' => str_contains($src['web'], "AKNotify.toast('warning', 'يرجى اختيار يتيم واحد على الأقل')"),
    'bulk member form uses requestSubmit and shared same-page POST handling' => str_contains($src['web'], "getElementById('bulkAddForm').requestSubmit()"),
    'mobile usage summary includes workflow history used by safe archive logic' => str_contains($src['api'], "'workflow_history' => (int)") && str_contains($src['api'], 'FROM group_workflow_audit_log') && str_contains($src['api'], "entity_type = 'orphan_groups'"),
    'web and API creation writes the audit event' => str_contains($src['web'], 'ak_vgm_log_group_created($pdo') && str_contains($src['api'], 'ak_vgm_log_group_created($pdo'),
    'group creation audit is excluded from used-group history' => str_contains($src['helper'], 'function ak_vgm_log_group_created')
        && str_contains($src['helper'], "action <> 'VGM_CREATE_GROUP'")
        && str_contains($src['api'], "action <> 'VGM_CREATE_GROUP'"),
];
$failed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . ' | ' . $label . PHP_EOL;
    if (!$ok) $failed++;
}
echo 'RESULT | ' . count($checks) . ' checks | ' . ($failed === 0 ? 'PASS' : 'FAIL') . PHP_EOL;
exit($failed === 0 ? 0 : 1);
