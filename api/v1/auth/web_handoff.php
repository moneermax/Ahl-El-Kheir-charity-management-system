<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

ak_api_require_method('POST');
ak_api_authenticated_user();

// The mobile product is native-only. Keep this endpoint as an explicit
// retirement response so older app builds cannot silently open website pages.
ak_api_json(410, ['ok' => false, 'error' => [
    'code' => 'native_only',
    'message' => 'Website handoff is disabled. Use a native mobile workflow.',
]]);
