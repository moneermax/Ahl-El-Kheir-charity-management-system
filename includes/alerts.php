<?php
$flashes = get_flashes();

foreach ($flashes as $flash) {
    $type = $flash['type'] ?? 'info';

    if ($type === 'error') {
        $type = 'danger';
    }

    if (!in_array($type, ['success', 'danger', 'warning', 'info'], true)) {
        $type = 'info';
    }

    $message = (string)($flash['message'] ?? '');
?>
    <div
        class="ak-flash-message d-none"
        data-ak-flash="<?php echo e($type); ?>"
        data-ak-message="<?php echo e($message); ?>"
        aria-live="polite"
    ><?php echo e($message); ?></div>
<?php
}
?>