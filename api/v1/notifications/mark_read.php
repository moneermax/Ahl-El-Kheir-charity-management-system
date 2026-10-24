<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
$user = ak_api_authenticated_user();
$input = ak_api_read_json();
$rawId = $input['notification_id'] ?? null;

if (
    (!is_int($rawId) && !(is_string($rawId) && ctype_digit($rawId)))
    || (int)$rawId < 1
) {
    ak_api_json(422, [
        'ok' => false,
        'error' => [
            'code' => 'invalid_notification_id',
            'message' => 'A valid notification ID is required.',
        ],
    ]);
}

dbExecute(
    'UPDATE notifications
     SET is_read = 1
     WHERE id = ? AND recipient_user_id = ? AND is_read = 0',
    [(int)$rawId, (int)$user['id']]
);

ak_api_json(200, [
    'ok' => true,
    'data' => ['marked_read' => true],
]);
