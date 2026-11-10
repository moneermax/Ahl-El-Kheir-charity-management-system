<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $content;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$approval = $read('modules/accounting/lib_sponsor_payment_approval.php');
$web = $read('modules/accounting/fm_review_queue.php');
$apiRead = $read('api/v1/accounting/fm_review_queue.php');
$apiAction = $read('api/v1/accounting/fm_review_queue_action.php');
$mobile = $read('mobile/ahl_el_kheir_app/lib/fm_review_queue_page.dart');

$assert(
    str_contains($approval, "return in_array(\$role, ['financial_manager', 'fm', 'finance', 'admin', 'sudo'], true);"),
    'The shared sponsor-payment review capability must include only the existing FM/Admin roles.'
);
$assert(
    str_contains($web, "'general_manager'") && str_contains($web, '$canReviewActions=ak_sponsor_payment_can_review($role);'),
    'GM must retain web queue read access while action capability is checked separately.'
);
$assert(
    str_contains($web, 'http_response_code(403);') && str_contains($web, '<?php if($canReviewActions): ?>'),
    'Web POST actions must be denied to read-only roles and action controls must be hidden.'
);
$assert(
    str_contains($apiRead, "'general_manager'") && str_contains($apiRead, "'can_review_actions' => $canReviewActions"),
    'Mobile queue GET must preserve GM read access and return the server-derived action capability.'
);
$assert(
    str_contains($apiAction, 'if (!ak_sponsor_payment_can_review((string)$user[\'role_code\']))'),
    'Mobile approval/return endpoint must use the shared action capability.'
);
$assert(
    str_contains($mobile, "canReviewActions = data['can_review_actions'] == true;")
        && str_contains($mobile, 'if (!past && canReviewActions)')
        && str_contains($mobile, 'if (!past && !canReviewActions)'),
    'Mobile UI must hide action controls and render a read-only notice when the server denies actions.'
);

echo "PASS | sponsor_payment_review_roles=PASS | web_read_only_guard=PASS | mobile_api_read_only_guard=PASS | mobile_ui_read_only=PASS\n";
