<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$source = file_get_contents($root . '/modules/transactions/create.php');
if (!is_string($source)) {
    throw new RuntimeException('Unable to read sponsorship payment form source.');
}

$helper = file_get_contents($root . '/config/database.php');
if (!is_string($helper)) {
    throw new RuntimeException('Unable to read database helper source.');
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(
    str_contains(
        $source,
        "FROM sponsor_payments WHERE sponsor_id = ? AND NOT (status='approved' AND transaction_id IS NOT NULL)"
    ),
    'Approved sponsor-payment submissions linked to a transaction must not be rendered as a second history row.'
);
$assert(
    str_contains($source, "FROM transactions WHERE sponsor_id = ? UNION ALL SELECT 'pending' AS src"),
    'The history must continue to render the canonical transaction row and retain separate submission rows.'
);
$assert(
    str_contains($helper, '$excludeLinkedApproved = str_contains($sql, $linkedApprovedFilter);')
        && str_contains($helper, "WHERE sponsor_id = ?' . (\$excludeLinkedApproved ? \$linkedApprovedFilter : '') . '"),
    'The collation-normalized history query must preserve the caller exclusion for linked approved submissions.'
);

echo "PASS | linked_approved_submission_hidden=PASS | transaction_history_preserved=PASS\n";
