<?php
declare(strict_types=1);

/**
 * Source-contract regression test for sponsor conversion success feedback.
 *
 * This is intentionally database-free. It guards the complete source path:
 * conversion POST -> native redirect -> one-time success flash -> 8-second toast.
 */
$root = dirname(__DIR__);

$read = static function (string $relativePath) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "FAIL | cannot read {$relativePath}\n");
        exit(1);
    }
    return $content;
};

$request = $read('modules/sponsors/my_request.php');
$appJs = $read('assets/js/app.js');
$notificationsJs = $read('assets/js/notifications.js');
$alerts = $read('includes/alerts.php');

$checks = [
    'conversion form bypasses same-page fetch interception' =>
        str_contains($request, '<form method="post" class="d-inline" data-ak-native-post="1"'),
    'conversion emits the long success flash before redirecting to sponsor detail' =>
        strpos($request, "flash('success_long',") !== false
        && strpos($request, "modules/sponsors/view.php?id=") !== false
        && strpos($request, "flash('success_long',") < strpos($request, "modules/sponsors/view.php?id="),
    'global POST handler honors the native-post opt-out' =>
        str_contains($appJs, "form.dataset.akNativePost === '1'"),
    'success_long is converted to a success toast with an 8000ms duration' =>
        str_contains($alerts, "if (\$type === 'success_long')")
        && str_contains($alerts, '$duration = 8000;')
        && str_contains($alerts, 'data-ak-duration="<?php echo (int)$duration; ?>"'),
    'normal page load reads and forwards the server-supplied toast duration' =>
        str_contains($notificationsJs, "var duration = Number(node.getAttribute('data-ak-duration') || 0);")
        && str_contains($notificationsJs, 'window.AKNotify.toast(type, message.trim(), duration);'),
    'toast implementation overrides the default timer when a duration is supplied' =>
        str_contains($notificationsJs, 'toastOptions.timer = Number(duration);'),
    'same-page response handling also preserves and forwards toast duration' =>
        str_contains($appJs, "duration: Number(node.getAttribute('data-ak-duration') || 0)")
        && str_contains($appJs, 'window.AKNotify.toast(flash.type, flash.message, flash.duration);'),
];

$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' | ' . $name . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}

if ($failed > 0) {
    fwrite(STDERR, "Sponsor conversion toast contract failed: {$failed} check(s).\n");
    exit(1);
}

echo 'PASS | sponsor conversion toast contract complete' . PHP_EOL;
