<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('GET');
$user = ak_api_authenticated_user();

$perPage = 30;
$page = max(1, (int)($_GET['page'] ?? 1));

$totalRow = dbFetchOne(
    'SELECT COUNT(*) AS total FROM notifications WHERE recipient_user_id = ?',
    [(int)$user['id']]
);
$total = (int)($totalRow['total'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$items = dbFetchAll(
    'SELECT id, title, body, created_at, is_read
     FROM notifications
     WHERE recipient_user_id = ?
     ORDER BY id DESC
     LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
    [(int)$user['id']]
);

$unreadRow = dbFetchOne(
    'SELECT COUNT(*) AS unread
     FROM notifications
     WHERE recipient_user_id = ? AND is_read = 0',
    [(int)$user['id']]
);

$items = array_map(
    static fn(array $item): array => [
        'id' => (int)$item['id'],
        'title' => (string)($item['title'] ?? ''),
        'body' => (string)($item['body'] ?? ''),
        'created_at' => (string)($item['created_at'] ?? ''),
        'is_read' => (int)($item['is_read'] ?? 0) === 1,
    ],
    $items
);

ak_api_json(200, [
    'ok' => true,
    'data' => [
        'items' => $items,
        'total' => $total,
        'unread' => (int)($unreadRow['unread'] ?? 0),
        'page' => $page,
        'pages' => $pages,
    ],
]);
