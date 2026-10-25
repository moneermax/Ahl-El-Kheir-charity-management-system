<?php
declare(strict_types=1);

// Retired by the native-only mobile policy. Never establish a browser session
// or redirect a mobile user into the website from this route.
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
echo 'Mobile website handoff is disabled. Return to the mobile app.';
exit;
